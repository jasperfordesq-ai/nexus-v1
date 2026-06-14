{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.guide.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.guide.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.guide.intro') }}</p>

            <h2 class="govuk-heading-l">{{ __('govuk_alpha.guide.equal_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.guide.equal_body') }}</p>

            <h2 class="govuk-heading-l">{{ __('govuk_alpha.guide.steps_title') }}</h2>
            <ol class="govuk-list govuk-list--number govuk-list--spaced">
                <li><span class="govuk-!-font-weight-bold">{{ __('govuk_alpha.guide.step1_title') }}.</span> {{ __('govuk_alpha.guide.step1_body') }}</li>
                <li><span class="govuk-!-font-weight-bold">{{ __('govuk_alpha.guide.step2_title') }}.</span> {{ __('govuk_alpha.guide.step2_body') }}</li>
                <li><span class="govuk-!-font-weight-bold">{{ __('govuk_alpha.guide.step3_title') }}.</span> {{ __('govuk_alpha.guide.step3_body') }}</li>
            </ol>

            <h2 class="govuk-heading-l">{{ __('govuk_alpha.guide.getting_started_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.guide.getting_started_body') }}</p>

            <div class="nexus-alpha-actions govuk-!-margin-top-4">
                @if ($isAuthenticated ?? false)
                    @if (\App\Core\TenantContext::hasModule('listings'))
                        <a class="govuk-button" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.guide.browse_listings') }}</a>
                    @endif
                    @if (\App\Core\TenantContext::hasModule('wallet'))
                        <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.guide.go_to_wallet') }}</a>
                    @endif
                @else
                    <a class="govuk-button" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.guide.create_account') }}</a>
                    @if (\App\Core\TenantContext::hasModule('listings'))
                        <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.guide.browse_listings') }}</a>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
